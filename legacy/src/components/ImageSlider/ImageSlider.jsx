import { useState } from 'react'
import React from 'react'
import './ImageSlider.css'
import arrowRight from '../../assets/icon/arrow-right.svg'
import arrowLeft from '../../assets/icon/arrow-left.svg'


const ImageSlider = ({slides}) => {

  const [currentIndex, setCurrentIndex]=useState(0);

  const slideStyles = {
    width: "100%",
    height: "100%",
    borderRadius: "10px",
    backgroundPosition: "center",
    backgroundSize: "cover",
    backgroundImage: `url(${slides[currentIndex]})`, 
  };

  const goToPrev = () => {
    const newIndex = (currentIndex === 0) ? slides.length - 1 : currentIndex - 1;
    setCurrentIndex(newIndex);
  };

  const goToNext = () => {
    const newIndex = (currentIndex === slides.length - 1) ? 0 : currentIndex + 1;
    setCurrentIndex(newIndex);
  };

  const goToSlide = (slideIndex) => {
    setCurrentIndex(slideIndex);
  }

  



  return (
    
    <div className="sliderStyles">
      <img className="rightArrow" src={arrowRight} alt="" onClick={goToNext}/>
      <img className="leftArrow" src={arrowLeft} alt="" onClick={goToPrev}/>
      
      <div style={slideStyles}></div>

      <div className="dotsContainer">
        {slides.map((slide, slideIndex) => (
          <div  className="dotStyles" 
                key={slideIndex} 
                onClick={() => goToSlide(slideIndex)}>•</div>
        ))}
      </div>
  
    </div>
  )
}

export default ImageSlider

/* 
<div className="dotsContainer">
        {slides.map((slide, slideIndex) => (
          <div  className="dotStyles" 
                key={slideIndex} 
                onClick={() => goToSlide(slideIndex)}>•</div>
        ))}
      </div>
*/