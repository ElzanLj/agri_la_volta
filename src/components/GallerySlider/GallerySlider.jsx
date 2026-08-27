import React from 'react';
import './GallerySlider.css';
import img1 from '../../assets/fotoDintorni/fotoAgri1.jpeg';
import img2 from '../../assets/fotoDintorni/fotoAgri2.jpeg';
import img3 from '../../assets/fotoDintorni/fotoAgri3.jpeg';
import img4 from '../../assets/fotoDintorni/fotoAgri4.jpeg';
import img5 from '../../assets/fotoDintorni/fotoAgri5.jpeg';

const ApartamentSlider = () => {
  const images = [img1, img2, img3, img4, img5, img1, img2, img3, img4, img5];

  return (
    <div className="gallery">
      <div className='gallery-slider'>
        <div className='gallery-slide-track'>
          {images.concat(images).map((img, index) => (
            <div key={index} className='gallery-slide'>
              <img className='gallery-img' src={img} alt={`Image ${index + 1}`} />
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

export default ApartamentSlider;
