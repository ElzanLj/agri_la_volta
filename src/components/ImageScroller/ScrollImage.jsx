  import React, { useState, useRef } from 'react';
import './Imagescroll.css';

const images = [
  { src: 'https://visitsalsomaggiore.it/it/wp-content/uploads/sites/2/2019/04/piscina05_large.jpg'},
  { src: 'https://lh3.googleusercontent.com/proxy/7WvYuJkJfE_XHinjqITbM7fuFRc1g7sGI0eykpKYxTazWaq4fo6N7oZTfSXk2fiT7CD0MfI6BnMug-vM4-KzkypedjWQHrSa7zFzXURgFSI'},
  { src: 'https://cf.bstatic.com/xdata/images/hotel/max1024x768/342176458.jpg?k=9d81c29b25274f9c2983e0aae95ea17e19b0e092822c92e861c307d73a13578a&o=&hp=1'},
  { src: 'https://dynamic-media-cdn.tripadvisor.com/media/photo-o/13/45/82/b4/la-volta.jpg?w=700&h=-1&s=1'}
];

function ImageScroller() {
  const [currentIndex, setCurrentIndex] = useState(0);
  const containerRef = useRef(null);

  const handleScroll = () => {
    const scrollPosition = containerRef.current.scrollTop;
    const scrollHeight = containerRef.current.scrollHeight - containerRef.current.clientHeight;
    const newIndex = Math.floor((scrollPosition / scrollHeight) * images.length);
    setCurrentIndex(newIndex);
  };

  return (
    <div className="container">
      <div className="image-container" ref={containerRef} onScroll={handleScroll}>
        {images.map((image, index) => (
          <div key={index} className="image" style={{ backgroundImage: `url(${image.src})` }}></div>
        ))}
      </div>
      <div className="navigator">
        {images.map((_, index) => (
          <div key={index} className={`dot ${index === currentIndex ? 'active' : ''}`} />
        ))}
      </div>
      <div className="info-container">
        <h1>L'Agriturismo La Volta</h1>
        <p>Si trova sulle splendide colline
            che coronano la pianura padana a circa due
            km dal rinomato centro termale di Salsomaggiore Terme, luogo dove la salute, il benessere e la cura della bellezza sono la principale caratteristica.</p>
      </div>
    </div>
  );
}

export default ImageScroller;
